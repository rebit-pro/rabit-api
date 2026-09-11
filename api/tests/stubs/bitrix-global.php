<?php

declare(strict_types=1);

namespace {
    if (!class_exists(CFile::class)) {
        final class CFile
        {
            /** @var array<int, array<string, mixed>> */
            private static array $mockFiles = [];
            private static int $nextFileId = 1;

            /**
             * @param array<string, mixed> $fileArray
             */
            public static function SaveFile(array $fileArray, string $moduleId = ''): int|false
            {
                $tmpName = (string)($fileArray['tmp_name'] ?? '');

                if ('' === $tmpName || !is_file($tmpName) || !is_readable($tmpName)) {
                    return false;
                }

                $fileId = self::$nextFileId++;
                $fileName = basename((string)($fileArray['name'] ?? $tmpName));
                $normalizedModuleId = '' !== $moduleId ? trim($moduleId, '/') : '';
                $src = '/upload/' . ('' !== $normalizedModuleId ? $normalizedModuleId . '/' : '') . $fileName;

                self::$mockFiles[$fileId] = [
                    'ID' => $fileId,
                    'MODULE_ID' => $moduleId,
                    'SRC' => $src,
                    'ORIGINAL_NAME' => $fileName,
                    'FILE_NAME' => $fileName,
                    'CONTENT_TYPE' => (string)($fileArray['type'] ?? 'application/octet-stream'),
                    'FILE_SIZE' => (int)($fileArray['size'] ?? (filesize($tmpName) ?: 0)),
                    'TMP_NAME' => $tmpName,
                ];

                return $fileId;
            }

            public static function Delete(int $fileId): void
            {
                unset(self::$mockFiles[$fileId]);
            }

            public static function GetPath(int $fileId): string
            {
                return (string)(self::$mockFiles[$fileId]['SRC'] ?? '');
            }

            /**
             * @return array<string, mixed>|false
             */
            public static function GetFileArray(int $fileId): array|false
            {
                return self::$mockFiles[$fileId] ?? false;
            }

            /**
             * @param array<string, mixed> $fileArray
             */
            public static function setMockFileArray(int $fileId, array $fileArray): void
            {
                self::$mockFiles[$fileId] = array_replace(
                    [
                        'ID' => $fileId,
                        'MODULE_ID' => (string)($fileArray['MODULE_ID'] ?? ''),
                        'SRC' => (string)($fileArray['SRC'] ?? ''),
                        'ORIGINAL_NAME' => (string)($fileArray['ORIGINAL_NAME'] ?? $fileArray['FILE_NAME'] ?? ''),
                        'FILE_NAME' => (string)($fileArray['FILE_NAME'] ?? $fileArray['ORIGINAL_NAME'] ?? ''),
                        'CONTENT_TYPE' => (string)($fileArray['CONTENT_TYPE'] ?? 'application/octet-stream'),
                        'FILE_SIZE' => (int)($fileArray['FILE_SIZE'] ?? $fileArray['FILE_SIZE_RAW'] ?? 0),
                    ],
                    $fileArray,
                );
                self::$nextFileId = max(self::$nextFileId, $fileId + 1);
            }

            public static function resetMockFiles(): void
            {
                self::$mockFiles = [];
                self::$nextFileId = 1;
            }
        }
    }

    if (!class_exists(CEvent::class)) {
        class CEvent
        {
            /** @var array{
             *     eventName: string,
             *     siteId: string|array,
             *     fields: array<string, mixed>,
             *     filesContent: array<int, array<string, string>>,
             * }|null */
            public static ?array $lastSendImmediateCall = null;
            public static string|false $sendImmediateResult = 'Y';

            /**
             * @param array<string, mixed>              $fields
             * @param array<int, int|string>            $files
             * @param array<int, array<string, string>> $filesContent
             */
            public static function SendImmediate(
                string $eventName,
                string|array $siteId,
                array $fields,
                string $duplicate = 'Y',
                string $messageId = '',
                array $files = [],
                string $languageId = '',
                array $filesContent = [],
            ): string|false {
                self::$lastSendImmediateCall = [
                    'eventName' => $eventName,
                    'siteId' => $siteId,
                    'fields' => $fields,
                    'filesContent' => $filesContent,
                ];

                return self::$sendImmediateResult;
            }
        }
    }

    if (!class_exists(CUser::class)) {
        class CUser
        {
            public string $LAST_ERROR = '';

            /** @var array<int, array<string, mixed>> */
            private static array $mockUsers = [];

            /**
             * @param array<string, mixed> $fields
             */
            public function Add(array $fields): int|false
            {
                return 1;
            }

            /**
             * @param array<string, mixed> $fields
             */
            public function Update(int $id, array $fields): bool
            {
                return true;
            }

            public static function GetByID(int $id): CDBResult
            {
                return new CDBResult(self::$mockUsers[$id] ?? null);
            }

            /**
             * @param array<string, mixed> $userData
             */
            public static function setMockUser(int $id, array $userData): void
            {
                self::$mockUsers[$id] = $userData;
            }

            public static function resetMockUsers(): void
            {
                self::$mockUsers = [];
            }
        }
    }

    if (!class_exists(CDBResult::class)) {
        class CDBResult
        {
            /** @var array<string, mixed>|null */
            private ?array $row;
            private bool $fetched = false;

            /**
             * @param array<string, mixed>|null $row
             */
            public function __construct(?array $row)
            {
                $this->row = $row;
            }

            /**
             * @return array<string, mixed>|false
             */
            public function Fetch(): array|false
            {
                if ($this->fetched || null === $this->row) {
                    return false;
                }

                $this->fetched = true;

                return $this->row;
            }
        }
    }
}
