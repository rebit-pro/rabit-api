<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Request;

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Morefoto\Media\Application\Photo\Dto\ListPhotosInputDto;
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

    public function listing(HttpRequest $request): ListPhotosInputDto
    {
        $query = $this->query($request);
        if ([] !== array_diff(array_keys($query), ['groupId', 'childCode', 'assigned', 'page', 'pageSize'])) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }
        $groupId = $query['groupId'] ?? null;
        if (null !== $groupId && (!is_string($groupId) || 1 !== preg_match('/^[a-f0-9-]{36}$/D', $groupId))) {
            throw new HttpException('INVALID_GROUP', 422);
        }
        $page = $this->positive($query, 'page', 1, 1000000);
        $pageSize = $this->positive($query, 'pageSize', 50, 100);
        $childCode = $query['childCode'] ?? null;
        if (null !== $childCode && (!is_string($childCode) || 1 !== preg_match('/^[A-Z]{1,3}$/D', $childCode))) {
            throw new HttpException('INVALID_CHILD_CODE', 422);
        }
        $assigned = $query['assigned'] ?? null;
        if (null !== $assigned && !in_array($assigned, ['true', 'false', '1', '0'], true)) {
            throw new HttpException('INVALID_ASSIGNED_FILTER', 422);
        }
        $noMatch = null !== $childCode || in_array($assigned, ['true', '1'], true);

        return new ListPhotosInputDto($groupId, $page, $pageSize, $noMatch);
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

    /** @return array<string,mixed> */
    private function query(HttpRequest $request): array
    {
        $query = [];
        parse_str((string)parse_url((string)$request->getRequestUri(), PHP_URL_QUERY), $query);

        return $query;
    }
}
