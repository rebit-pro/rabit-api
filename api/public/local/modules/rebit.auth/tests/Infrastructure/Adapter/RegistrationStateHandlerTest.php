<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Infrastructure\Adapter;

use PHPUnit\Framework\TestCase;
use Rebit\Auth\Infrastructure\Bitrix\Event\RegistrationStateHandler;

/**
 * @internal
 */
final class RegistrationStateHandlerTest extends TestCase
{
    public function testDisableWithoutNativeUserIdFailsClosed(): void
    {
        $fields = ['ACTIVE' => 'N', 'UF_AUTH_REGISTRATION_PENDING' => 1];
        $this->expectException(\LogicException::class);
        RegistrationStateHandler::onBeforeUserUpdate($fields);
    }

    public function testCredentialRefreshDoesNotChangeActivationState(): void
    {
        $fields = ['PASSWORD' => 'test-password', 'NAME' => 'User'];
        $original = $fields;
        RegistrationStateHandler::onBeforeUserUpdate($fields);
        self::assertSame($original, $fields);
    }

    public function testExplicitActivationAlsoFinishesPending(): void
    {
        $fields = ['ACTIVE' => 'Y', 'UF_AUTH_REGISTRATION_PENDING' => 1];
        RegistrationStateHandler::onBeforeUserUpdate($fields);
        self::assertSame(0, $fields['UF_AUTH_REGISTRATION_PENDING']);
    }
}
