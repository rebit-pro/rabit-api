<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Request;

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Morefoto\Handoff\Application\Request\Dto\ClarificationInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestListInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationInputDto;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class StaffRequestFactory
{
    public function listing(HttpRequest $request): StaffRequestListInputDto
    {
        $query = $this->query($request);
        if ([] !== array_diff(array_keys($query), ['institutionId', 'shootId', 'status', 'page', 'pageSize'])) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }
        $institutionId = $this->optionalUuid($query['institutionId'] ?? null);
        $shootId = $this->optionalUuid($query['shootId'] ?? null);
        $status = $query['status'] ?? null;
        if (null !== $status && (!is_string($status) || !in_array($status, ['submitted', 'clarification', 'transferred'], true))) {
            throw new HttpException('INVALID_STATUS', 422);
        }

        return new StaffRequestListInputDto(
            $institutionId,
            $shootId,
            $status,
            $this->positive($query, 'page', 1, 1000000),
            $this->positive($query, 'pageSize', 25, 100),
        );
    }

    /** @return array{input:StaffRequestMutationInputDto,key:IdempotencyKey} */
    public function mutation(HttpRequest $request, bool $create): array
    {
        $fields = ['institutionId', 'shootId', 'rows', 'comment'];
        if (!$create) {
            $fields[] = 'revision';
        }
        $data = $this->json($request, $fields);
        $institutionId = $data['institutionId'] ?? null;
        $shootId = $data['shootId'] ?? null;
        $rows = $data['rows'] ?? null;
        $comment = $data['comment'] ?? null;
        $revision = $data['revision'] ?? null;
        if (!$this->uuid($institutionId) || !$this->uuid($shootId) || !is_array($rows) || [] === $rows || 30 < count($rows)
            || !is_string($comment) || 500 < mb_strlen($comment)
            || (!$create && (!is_int($revision) || 1 > $revision))) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }
        $normalized = [];
        $ids = [];
        foreach ($rows as $row) {
            if (!$row instanceof \stdClass) {
                throw new HttpException('INVALID_ROW', 422);
            }
            $value = get_object_vars($row);
            $rowFields = ['id', 'groupId', 'code'];
            if ([] !== array_diff(array_keys($value), $rowFields) || [] !== array_diff($rowFields, array_keys($value))
                || !$this->uuid($value['id'] ?? null)
                || !$this->uuid($value['groupId'] ?? null) || !is_string($value['code'] ?? null)) {
                throw new HttpException('INVALID_ROW', 422);
            }
            $code = strtoupper(trim($value['code']));
            if (1 !== preg_match('/^[A-Z]{1,3}(?:[0-9]{3})?$/D', $code) || isset($ids[$value['id']])) {
                throw new HttpException('INVALID_ROW', 422);
            }
            $ids[$value['id']] = true;
            $normalized[] = ['id' => $value['id'], 'groupId' => $value['groupId'], 'code' => $code];
        }

        return [
            'input' => new StaffRequestMutationInputDto($institutionId, $shootId, $normalized, trim($comment), $create ? null : $revision),
            'key' => new IdempotencyKey((string)$request->getHeader('Idempotency-Key')),
        ];
    }

    /** @return array{input:ClarificationInputDto,key:IdempotencyKey} */
    public function clarification(HttpRequest $request): array
    {
        $data = $this->json($request, ['revision', 'comment', 'confirmed']);
        $revision = $data['revision'] ?? null;
        $comment = $data['comment'] ?? null;
        $confirmed = $data['confirmed'] ?? null;
        if (!is_int($revision) || 1 > $revision || !is_string($comment) || 5 > mb_strlen(trim($comment))
            || 500 < mb_strlen($comment) || !is_bool($confirmed)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return ['input' => new ClarificationInputDto($revision, trim($comment), $confirmed), 'key' => new IdempotencyKey((string)$request->getHeader('Idempotency-Key'))];
    }

    public function routeId(): string
    {
        $application = Application::getInstance();
        $value = $application->hasCurrentRoute() ? $application->getCurrentRoute()->getParameterValue('staff_request_id') : null;
        if (!$this->uuid($value)) {
            throw new HttpException('INVALID_ROUTE', 400);
        }

        return $value;
    }

    /** @param list<string> $fields @return array<string,mixed> */
    private function json(HttpRequest $request, array $fields): array
    {
        if ([] !== $this->query($request) || 'application/json' !== strtolower(trim(explode(';', (string)$request->getHeader('Content-Type'))[0]))) {
            throw new HttpException('JSON_REQUIRED', 400);
        }
        $raw = (string)$request->getInput();
        if (32768 < strlen($raw)) {
            throw new HttpException('PAYLOAD_TOO_LARGE', 413);
        }
        try {
            $decoded = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new HttpException('MALFORMED_JSON', 400, $error);
        }
        if (!$decoded instanceof \stdClass) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }
        $data = get_object_vars($decoded);
        if ([] !== array_diff(array_keys($data), $fields) || [] !== array_diff($fields, array_keys($data))) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function query(HttpRequest $request): array
    {
        $query = [];
        parse_str((string)parse_url((string)$request->getRequestUri(), PHP_URL_QUERY), $query);

        return $query;
    }

    /** @param array<string,mixed> $query */
    private function positive(array $query, string $field, int $default, int $maximum): int
    {
        if (!array_key_exists($field, $query)) {
            return $default;
        }
        $value = $query[$field];
        if (!is_scalar($value) || 1 !== preg_match('/^[1-9][0-9]{0,6}$/D', (string)$value) || $maximum < (int)$value) {
            throw new HttpException('INVALID_PAGE', 422);
        }

        return (int)$value;
    }

    private function optionalUuid(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$this->uuid($value)) {
            throw new HttpException('INVALID_FILTER', 422);
        }

        return $value;
    }

    private function uuid(mixed $value): bool
    {
        return is_string($value) && 1 === preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value);
    }
}
