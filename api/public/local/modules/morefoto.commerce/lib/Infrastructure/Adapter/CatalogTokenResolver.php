<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Adapter;

use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class CatalogTokenResolver implements TokenResolverInterface
{
    public function __construct(private TokenResolverInterface $tokens) {}

    public function resolveUserId(string $token): int
    {
        try {
            return $this->tokens->resolveUserId($token);
        } catch (HttpException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new HttpException('Authentication service is unavailable.', 503);
        }
    }
}
