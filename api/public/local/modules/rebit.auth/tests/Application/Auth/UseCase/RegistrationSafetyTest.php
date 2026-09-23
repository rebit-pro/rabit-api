<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\UseCase;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Access\Dto\AcceptInvitationInputDto;
use Rebit\Auth\Application\Access\Service\AccessLinkGuard;
use Rebit\Auth\Application\Access\Service\SessionIssuer;
use Rebit\Auth\Application\Access\UseCase\AcceptAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\IssueAccessInvitationUseCase;
use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;
use Rebit\Auth\Application\Auth\Contract\RegistrationConfirmationMailerInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\Dto\Request\ConfirmRegistrationRequestDto;
use Rebit\Auth\Application\Auth\Dto\Request\RequestRegistrationCodeRequestDto;
use Rebit\Auth\Application\Auth\UseCase\ConfirmRegistrationUseCase;
use Rebit\Auth\Application\Auth\UseCase\RequestRegistrationCodeUseCase;
use Rebit\Auth\Domain\Access\Service\PasswordPolicy;
use Rebit\Auth\Domain\Registration\Entity\RegistrationConfirmation;
use Rebit\Auth\Domain\Registration\Repository\RegistrationConfirmationRepository;
use Rebit\Auth\Domain\Registration\Service\RegistrationCodeGenerator;
use Rebit\Auth\Domain\User\Entity\UserRegistrationState;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Auth\Tests\Application\Access\InMemoryAccessAccounts;
use Rebit\Auth\Tests\Application\Access\InMemoryAccessLinks;
use Rebit\Auth\Tests\Application\Access\RecordingAccessMailer;
use Rebit\Auth\Tests\Application\Access\RecordingSessions;
use Rebit\Auth\Tests\Application\Access\SequenceAccessTokens;
use Rebit\Auth\Tests\Application\Access\SequenceSessionTokens;
use Rebit\Auth\Tests\Support\FrozenClock;
use Rebit\Auth\Tests\Support\ImmediateTransaction;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../../Access/AccessFakes.php';

/**
 * @internal
 */
final class RegistrationSafetyTest extends TestCase
{
    private const int NOW = 1800000000;

    private function confirmation(int $expiry = self::NOW + 60, ?DateTime $confirmedAt = null, int $attempts = 0): RegistrationConfirmation
    {
        $now = DateTime::createFromTimestamp(self::NOW);

        return new RegistrationConfirmation(
            1,
            7,
            'user@example.test',
            password_hash('123456', PASSWORD_DEFAULT),
            DateTime::createFromTimestamp($expiry),
            $now,
            $attempts,
            $confirmedAt,
            $now,
            $now,
        );
    }

    #[DataProvider('forbiddenUsers')]
    public function testRequestCodeDoesNotChangeForbiddenUser(bool $active, bool $pending, string $email): void
    {
        $users = $this->createMock(UserRepository::class);
        $user = new UserRegistrationState(7, $email, 'User', $active, $pending);
        $users->method('findByEmail')->willReturn($user);
        $users->method('findByIdForUpdate')->willReturn($user);
        $users->expects($this->never())->method('updateInactiveCredentials');
        $users->expects($this->never())->method('createInactiveUser');
        $confirmations = $this->createMock(RegistrationConfirmationRepository::class);
        $confirmations->method('findByEmail')->willReturn($this->confirmation());
        $confirmations->expects($this->never())->method('updateForResend');
        $mailer = $this->createMock(RegistrationConfirmationMailerInterface::class);
        $mailer->expects($this->never())->method('sendConfirmationCode');
        $useCase = new RequestRegistrationCodeUseCase($users, $confirmations, new RegistrationCodeGenerator(), $mailer, 15, 60, new FrozenClock(), new ImmediateTransaction());
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);
        $useCase->execute(new RequestRegistrationCodeRequestDto('user@example.test', 'password123'));
    }

    #[DataProvider('forbiddenUsers')]
    public function testConfirmationNeverActivatesForbiddenUser(bool $active, bool $pending, string $email): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findByIdForUpdate')->willReturn(new UserRegistrationState(7, $email, 'User', $active, $pending));
        $users->expects($this->never())->method('activateUser');
        $users->expects($this->never())->method('updateToken');
        $confirmations = $this->createMock(RegistrationConfirmationRepository::class);
        $confirmations->method('findByEmail')->willReturn($this->confirmation());
        $confirmations->expects($this->never())->method('markConfirmed');
        $useCase = new ConfirmRegistrationUseCase($users, $confirmations, $this->createStub(TokenGeneratorInterface::class), 24, 5, new FrozenClock(), new ImmediateTransaction());
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(409);
        $useCase->execute(new ConfirmRegistrationRequestDto('user@example.test', '123456'));
    }

    /** @return iterable<string, array{bool, bool, string}> */
    public static function forbiddenUsers(): iterable
    {
        yield 'legacy disabled' => [false, false, 'user@example.test'];
        yield 'already active' => [true, false, 'user@example.test'];
        yield 'active inconsistent pending' => [true, true, 'user@example.test'];
        yield 'different email' => [false, true, 'changed@example.test'];
    }

    /**
     * B4: the registration code stays a backend path. An account it activated must not be taken over by the
     * invitation link still sitting in the mailbox; the reverse direction is the "already active" case above.
     */
    public function testStaleInvitationCannotTakeOverAccountActivatedByCode(): void
    {
        $accounts = new InMemoryAccessAccounts();
        $accounts->add(7, 'user@example.test', active: false, pending: true);
        $links = new InMemoryAccessLinks();
        $mailer = new RecordingAccessMailer();
        $sessions = new RecordingSessions();
        $clock = new FrozenClock(self::NOW);
        (new IssueAccessInvitationUseCase($accounts, $links, new SequenceAccessTokens(), $mailer, $clock, 168, 60))->execute(7, 1);
        $accounts->activate(7);
        $passwordHash = $accounts->accounts[7]->passwordHash;
        $accept = new AcceptAccessInvitationUseCase(
            $links,
            $accounts,
            new AccessLinkGuard(),
            new PasswordPolicy(),
            new SessionIssuer(new SequenceSessionTokens(), $sessions, $clock, 24),
            $clock,
            new ImmediateTransaction(),
        );

        try {
            $accept->execute(new AcceptInvitationInputDto($mailer->sent[0][1]->token, 'someone else password'));
            self::fail('A stale invitation must not change the password of an active account.');
        } catch (HttpException $error) {
            self::assertSame('LINK_USED', $error->getMessage());
            self::assertSame(410, $error->getCode());
        }
        self::assertSame($passwordHash, $accounts->accounts[7]->passwordHash);
        self::assertSame([], $sessions->tokens);
    }

    public function testCodeAtExactDeadlineIsExpired(): void
    {
        $confirmations = $this->createStub(RegistrationConfirmationRepository::class);
        $confirmations->method('findByEmail')->willReturn($this->confirmation(self::NOW));
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->never())->method('activateUser');
        $useCase = new ConfirmRegistrationUseCase($users, $confirmations, $this->createStub(TokenGeneratorInterface::class), 24, 5, new FrozenClock(), new ImmediateTransaction());
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(410);
        $useCase->execute(new ConfirmRegistrationRequestDto('user@example.test', '123456'));
    }

    public function testConfirmedCodeCannotBeReplayed(): void
    {
        $confirmations = $this->createStub(RegistrationConfirmationRepository::class);
        $confirmations->method('findByEmail')->willReturn($this->confirmation(confirmedAt: DateTime::createFromTimestamp(self::NOW)));
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->never())->method('updateToken');
        $useCase = new ConfirmRegistrationUseCase($users, $confirmations, $this->createStub(TokenGeneratorInterface::class), 24, 5, new FrozenClock(), new ImmediateTransaction());
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $useCase->execute(new ConfirmRegistrationRequestDto('user@example.test', '123456'));
    }

    public function testAttemptLimitPreventsAnotherPasswordCheck(): void
    {
        $confirmations = $this->createMock(RegistrationConfirmationRepository::class);
        $confirmations->method('findByEmail')->willReturn($this->confirmation(attempts: 5));
        $confirmations->expects($this->never())->method('incrementAttempts');
        $useCase = new ConfirmRegistrationUseCase($this->createStub(UserRepository::class), $confirmations, $this->createStub(TokenGeneratorInterface::class), 24, 5, new FrozenClock(), new ImmediateTransaction());
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(429);
        $useCase->execute(new ConfirmRegistrationRequestDto('user@example.test', '123456'));
    }

    public function testWrongCodeIsReportedAfterTransactionCommitted(): void
    {
        $transaction = new class implements AuthTransactionInterface {
            public bool $committed = false;

            public function run(callable $operation): mixed
            {
                $result = $operation();
                $this->committed = true;

                return $result;
            }
        };
        $confirmations = $this->createMock(RegistrationConfirmationRepository::class);
        $confirmations->method('findByEmail')->willReturn($this->confirmation());
        $confirmations->expects($this->once())->method('incrementAttempts')->with(1);
        $useCase = new ConfirmRegistrationUseCase($this->createStub(UserRepository::class), $confirmations, $this->createStub(TokenGeneratorInterface::class), 24, 5, new FrozenClock(), $transaction);
        try {
            $useCase->execute(new ConfirmRegistrationRequestDto('user@example.test', '999999'));
            self::fail('Invalid code was accepted.');
        } catch (HttpException $exception) {
            self::assertSame(400, $exception->getCode());
            self::assertTrue($transaction->committed);
        }
    }

    public function testPendingResendPreservesPendingAndSendsOnlyAfterCommit(): void
    {
        $transaction = new class implements AuthTransactionInterface {
            public bool $committed = false;

            public function run(callable $operation): mixed
            {
                $result = $operation();
                $this->committed = true;

                return $result;
            }
        };
        $users = $this->createMock(UserRepository::class);
        $user = new UserRegistrationState(7, 'user@example.test', 'User', false, true);
        $users->method('findByEmail')->willReturn($user);
        $users->method('findByIdForUpdate')->willReturn($user);
        $users->expects($this->once())->method('updateInactiveCredentials')->with(7, 'password123', 'User');
        $users->expects($this->never())->method('activateUser');
        $confirmations = $this->createMock(RegistrationConfirmationRepository::class);
        $confirmations->method('findByEmail')->willReturn($this->confirmation());
        $confirmations->expects($this->once())->method('updateForResend');
        $mailer = $this->createMock(RegistrationConfirmationMailerInterface::class);
        $mailer->expects($this->once())->method('sendConfirmationCode')->willReturnCallback(static function(string $email, string $code, DateTime $expiry) use ($transaction): void {
            self::assertTrue($transaction->committed);
            self::assertSame('user@example.test', $email);
            self::assertSame(6, strlen($code));
            self::assertSame(self::NOW + 900, $expiry->getTimestamp());
        });
        $useCase = new RequestRegistrationCodeUseCase($users, $confirmations, new RegistrationCodeGenerator(), $mailer, 15, 60, new FrozenClock(), $transaction);
        $useCase->execute(new RequestRegistrationCodeRequestDto('user@example.test', 'password123'));
    }
}
