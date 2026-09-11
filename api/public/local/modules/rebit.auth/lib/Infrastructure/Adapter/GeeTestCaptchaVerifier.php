<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Bitrix\Main\ArgumentException;
use Psr\Log\LoggerInterface;
use Rebit\Auth\Application\Auth\Contract\CaptchaVerifierInterface;
use Rebit\Auth\Application\Auth\Dto\Request\LoginCaptchaRequestDto;
use Rebit\Share\Infrastructure\HttpClient\Exception\HttpClientException;
use Rebit\Share\Infrastructure\HttpClient\RebitHttpClient;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class GeeTestCaptchaVerifier implements CaptchaVerifierInterface
{
    private const string VALIDATE_PATH = '/validate';

    public function __construct(
        private RebitHttpClient $httpClient,
        private LoggerInterface $logger,
        private string $captchaId,
        private string $captchaKey,
        private bool $enabled,
        private bool $bypass,
        private string $apiBaseUrl,
    ) {}

    /**
     * @throws HttpException
     */
    public function verify(?LoginCaptchaRequestDto $captcha): void
    {
        if (true === $this->bypass || false === $this->enabled) {
            return;
        }

        if (null === $captcha) {
            throw new HttpException('Captcha verification required', 400);
        }

        if ('' === $this->captchaId || '' === $this->captchaKey) {
            $this->logger->error('GeeTest captcha credentials are not configured');

            throw new HttpException('Captcha service unavailable', 503);
        }

        try {
            $response = $this->httpClient->post(
                $this->buildValidateUrl(),
                [
                    'lot_number' => $captcha->lot_number,
                    'captcha_output' => $captcha->captcha_output,
                    'pass_token' => $captcha->pass_token,
                    'gen_time' => $captcha->gen_time,
                    'sign_token' => hash_hmac('sha256', $captcha->lot_number, $this->captchaKey),
                ],
                [
                    'Accept' => 'application/json',
                ],
            );
        } catch (ArgumentException|HttpClientException $exception) {
            $this->logger->error('GeeTest captcha verification request failed', [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ]);

            throw new HttpException('Captcha service unavailable', 503, $exception);
        }

        if (!$this->isSuccessfulResponse($response)) {
            $this->logger->warning('GeeTest captcha verification failed', [
                'response' => $response,
            ]);

            throw new HttpException('Captcha verification failed', 400);
        }
    }

    private function buildValidateUrl(): string
    {
        return sprintf(
            '%s%s?captcha_id=%s',
            rtrim($this->apiBaseUrl, '/'),
            self::VALIDATE_PATH,
            urlencode($this->captchaId),
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isSuccessfulResponse(array $response): bool
    {
        return 'success' === ($response['result'] ?? null);
    }
}
