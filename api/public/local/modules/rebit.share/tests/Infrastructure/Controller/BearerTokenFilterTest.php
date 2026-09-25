<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Controller;

use Bitrix\Main\Event;
use Bitrix\Main\HttpRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerTrait;
use Rebit\Share\Infrastructure\Controller\Filters\BearerTokenFilter;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class BearerTokenFilterTest extends TestCase
{
    #[DataProvider('missingBearers')]
    public function testRequiredFilterRefusesMissingBearerWithCode(?string $header): void
    {
        $resolver = $this->createMock(TokenResolverInterface::class);
        $resolver->expects(self::never())->method('resolveUserId');

        try {
            (new BearerTokenFilter($resolver))->onBeforeAction($this->event($header));
            self::fail('UNAUTHORIZED expected.');
        } catch (HttpException $error) {
            self::assertSame(['UNAUTHORIZED', 401], [$error->getMessage(), $error->getCode()]);
        }
    }

    /** @return iterable<string, array{?string}> */
    public static function missingBearers(): iterable
    {
        yield 'no header' => [null];
        yield 'empty header' => [''];
        yield 'other scheme' => ['Basic dXNlcjpwYXNz'];
        yield 'empty token' => ['Bearer '];
    }

    public function testOptionalFilterLeavesGuestWithoutUser(): void
    {
        $resolver = $this->createMock(TokenResolverInterface::class);
        $resolver->expects(self::never())->method('resolveUserId');
        $event = $this->event(null);

        self::assertNull((new BearerTokenFilter($resolver, false))->onBeforeAction($event));
        self::assertNull($event->getParameter('controller')->getAuthUserIdOrNull());
    }

    public function testResolvedBearerAuthenticatesController(): void
    {
        $resolver = $this->createMock(TokenResolverInterface::class);
        $resolver->expects(self::once())->method('resolveUserId')->with('token-value')->willReturn(42);
        $event = $this->event('Bearer token-value');

        (new BearerTokenFilter($resolver))->onBeforeAction($event);

        self::assertSame(42, $event->getParameter('controller')->getAuthUserId());
    }

    public function testControllerWithoutUserRefusesWithCode(): void
    {
        try {
            $this->controller(null)->getAuthUserId();
            self::fail('UNAUTHORIZED expected.');
        } catch (HttpException $error) {
            self::assertSame(['UNAUTHORIZED', 401], [$error->getMessage(), $error->getCode()]);
        }
    }

    private function event(?string $header): Event
    {
        return new Event(['controller' => $this->controller($header)]);
    }

    private function controller(?string $header): AuthenticatedControllerInterface
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getHeader')->willReturn($header);

        return new class($request) implements AuthenticatedControllerInterface {
            use AuthenticatedControllerTrait;

            public function __construct(private readonly HttpRequest $request) {}

            public function getRequest(): HttpRequest
            {
                return $this->request;
            }
        };
    }
}
