<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Request;

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Morefoto\Media\Application\Photo\Dto\AssignPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\SetCoverInputDto;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class MediaRequestFactory
{
    /** @return array{tmpName:string,filename:string,bytes:int,groupId:string,fingerprint:?string} */
    public function upload(HttpRequest $request): array
    {
        if (1 !== preg_match('/^multipart\/form-data(?:\s*;|$)/i', (string)$request->getHeader('Content-Type'))) {
            throw new HttpException('MULTIPART_REQUIRED', 400);
        }
        $files = $request->getFileList()->getValues();
        if (1 !== count($files) || !is_array($files['file'] ?? null)) {
            throw new HttpException('ONE_PHOTO_REQUIRED', 422);
        }
        $file = $files['file'];
        if (UPLOAD_ERR_OK !== (int)($file['error'] ?? UPLOAD_ERR_NO_FILE)) {
            throw new HttpException('PHOTO_UPLOAD_FAILED', 422);
        }
        $groupId = $request->getPost('groupId');
        $fingerprint = $request->getPost('fingerprint');
        if (!is_string($groupId) || 1 !== preg_match('/^[a-f0-9-]{36}$/D', $groupId)) {
            throw new HttpException('GROUP_REQUIRED', 422);
        }
        if (null !== $fingerprint && !is_string($fingerprint)) {
            throw new HttpException('INVALID_FINGERPRINT', 422);
        }

        return [
            'tmpName' => (string)($file['tmp_name'] ?? ''),
            'filename' => (string)($file['name'] ?? ''),
            'bytes' => (int)($file['size'] ?? 0),
            'groupId' => $groupId,
            'fingerprint' => null === $fingerprint || '' === $fingerprint ? null : strtolower($fingerprint),
        ];
    }

    /** @return array{input:AssignPhotosInputDto,key:IdempotencyKey} */
    public function assignment(HttpRequest $request): array
    {
        $data = $this->json($request, ['shootId', 'revision', 'photoIds', 'childCode']);
        $shootId = $data['shootId'] ?? null;
        $revision = $data['revision'] ?? null;
        $photoIds = $data['photoIds'] ?? null;
        $childCode = $data['childCode'] ?? null;
        if (!is_string($shootId) || 1 !== preg_match('/^[a-f0-9-]{36}$/D', $shootId)
            || !is_int($revision) || 1 > $revision
            || !is_array($photoIds) || [] === $photoIds || 100 < count($photoIds)
            || !is_string($childCode) || 1 !== preg_match('/^[A-Z]{1,3}$/D', $childCode)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }
        $normalized = [];
        foreach ($photoIds as $photoId) {
            if (!is_string($photoId) || 1 !== preg_match('/^[a-f0-9-]{36}$/D', $photoId) || isset($normalized[$photoId])) {
                throw new HttpException('INVALID_PHOTO_IDS', 422);
            }
            $normalized[$photoId] = true;
        }

        return [
            'input' => new AssignPhotosInputDto($shootId, $revision, array_keys($normalized), $childCode),
            'key' => new IdempotencyKey((string)$request->getHeader('Idempotency-Key')),
        ];
    }

    /** @return array{input:SetCoverInputDto,key:IdempotencyKey} */
    public function cover(HttpRequest $request): array
    {
        $data = $this->json($request, ['revision', 'photoId']);
        $revision = $data['revision'] ?? null;
        $photoId = $data['photoId'] ?? null;
        if (!is_int($revision) || 1 > $revision || !is_string($photoId)
            || 1 !== preg_match('/^[a-f0-9-]{36}$/D', $photoId)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return [
            'input' => new SetCoverInputDto($revision, $photoId),
            'key' => new IdempotencyKey((string)$request->getHeader('Idempotency-Key')),
        ];
    }

    public function routeId(string $name): string
    {
        $application = Application::getInstance();
        $value = $application->hasCurrentRoute() ? $application->getCurrentRoute()->getParameterValue($name) : null;
        if (!is_string($value) || 1 !== preg_match('/^[a-f0-9-]{36}$/D', $value)) {
            throw new HttpException('INVALID_ROUTE', 400);
        }

        return $value;
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string,mixed>
     */
    private function json(HttpRequest $request, array $fields): array
    {
        if ([] !== $this->query($request)
            || 'application/json' !== strtolower(trim(explode(';', (string)$request->getHeader('Content-Type'))[0]))) {
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
}
