<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\HttpClient;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Psr\Log\LoggerInterface;
use Rebit\Share\Infrastructure\HttpClient\Exception\HttpClientException;

final class RebitHttpClient
{
    private ?string $authUser = null;
    private ?string $authPassword = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpClient $httpClient,
    ) {}

    public function setAuthorization(string $user, string $password): void
    {
        $this->authUser = $user;
        $this->authPassword = $password;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     *
     * @throws HttpClientException
     * @throws ArgumentException
     * @throws \JsonException
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, [], $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     *
     * @throws HttpClientException
     * @throws ArgumentException
     * @throws \JsonException
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * @param array<string, string> $data
     * @param array<string, array{
     *     path: string,
     *     name: string,
     *     mimeType: string,
     * }> $files
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     *
     * @throws HttpClientException
     * @throws ArgumentException
     * @throws \JsonException
     */
    public function postMultipart(string $url, array $data = [], array $files = [], array $headers = []): array
    {
        $multipartBody = $data;

        foreach ($files as $fieldName => $file) {
            $multipartBody[$fieldName] = new \CURLFile(
                $file['path'],
                $file['mimeType'],
                $file['name'],
            );
        }

        return $this->request('POST', $url, $multipartBody, $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     *
     * @throws ArgumentException|HttpClientException
     * @throws \JsonException
     */
    private function request(string $method, string $url, array $data, array $headers): array
    {
        $this->prepareClient($headers);

        $body = $this->prepareBody($data, $headers);

        $this->logger->debug('HTTP Request', [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ]);

        $result = 'GET' === $method
            ? $this->httpClient->get($url)
            : $this->httpClient->post($url, $body);

        return $this->handleResponse($result, $url, $method);
    }

    /**
     * @param array<string, string> $headers
     */
    private function prepareClient(array $headers): void
    {
        $this->httpClient->clearHeaders();

        foreach ($headers as $name => $value) {
            $this->httpClient->setHeader($name, $value);
        }

        if (null !== $this->authUser && null !== $this->authPassword) {
            $this->httpClient->setAuthorization($this->authUser, $this->authPassword);
        }
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>|string
     *
     * @throws ArgumentException
     * @throws \JsonException
     */
    private function prepareBody(array $data, array $headers): array|string
    {
        if ([] === $data) {
            return '';
        }

        if ($this->isJsonContentType($headers)) {
            return Json::encode($data);
        }

        return $data;
    }

    /**
     * @param array<string, string> $headers
     */
    private function isJsonContentType(array $headers): bool
    {
        return array_any($headers, fn($value, $name) => 'content-type' === strtolower($name) && str_contains(strtolower($value), 'application/json'));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws HttpClientException
     */
    private function handleResponse(false|string $result, string $url, string $method): array
    {
        $status = $this->httpClient->getStatus();
        $errors = $this->httpClient->getError();

        $this->logger->debug('HTTP Response', [
            'status' => $status,
            'url' => $url,
            'method' => $method,
            'response' => $result,
        ]);

        if (false === $result || [] !== $errors) {
            $errorMessage = implode('; ', $errors);
            $this->logger->error('HTTP Request failed', [
                'url' => $url,
                'errors' => $errors,
            ]);

            throw new HttpClientException("HTTP request failed: {$errorMessage}");
        }

        if ($status >= 400) {
            $this->logger->error('HTTP Error response', [
                'url' => $url,
                'status' => $status,
                'body' => $result,
            ]);

            throw new HttpClientException("HTTP error: status {$status}", $status);
        }

        return $this->parseResponse($result);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws HttpClientException
     */
    private function parseResponse(string $response): array
    {
        if ('' === $response) {
            return [];
        }

        try {
            return Json::decode($response);
        } catch (\Exception $e) {
            throw new HttpClientException(
                message: sprintf('Failed to parse JSON response: %s', $e->getMessage()),
                code: 0,
                previous: $e,
            );
        }
    }
}
