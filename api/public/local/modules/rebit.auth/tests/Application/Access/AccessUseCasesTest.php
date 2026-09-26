<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Access;

use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Access\Dto\AcceptInvitationInputDto;
use Rebit\Auth\Application\Access\Dto\ChangePasswordInputDto;
use Rebit\Auth\Application\Access\Dto\ConfirmPasswordResetInputDto;
use Rebit\Auth\Application\Access\Dto\PasswordResetInputDto;
use Rebit\Auth\Application\Access\Service\AccessLinkGuard;
use Rebit\Auth\Application\Access\Service\SessionIssuer;
use Rebit\Auth\Application\Access\UseCase\AcceptAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\ChangePasswordUseCase;
use Rebit\Auth\Application\Access\UseCase\ConfirmPasswordResetUseCase;
use Rebit\Auth\Application\Access\UseCase\GetAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\IssueAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\RequestPasswordResetUseCase;
use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;
use Rebit\Auth\Domain\Access\Service\EmailMask;
use Rebit\Auth\Domain\Access\Service\PasswordPolicy;
use Rebit\Auth\Tests\Support\FrozenClock;
use Rebit\Auth\Tests\Support\ImmediateTransaction;
use Rebit\Auth\Tests\Support\RecordingConsents;
use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/AccessFakes.php';

/**
 * @internal
 */
final class AccessUseCasesTest extends TestCase
{
    private const int NOW = 1800000000;

    private InMemoryAccessAccounts $accounts;
    private InMemoryAccessLinks $links;
    private RecordingAccessMailer $mailer;
    private RecordingSessions $sessions;
    private IssueAccessInvitationUseCase $issue;
    private RecordingConsents $consents;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryAccessAccounts();
        $this->links = new InMemoryAccessLinks();
        $this->mailer = new RecordingAccessMailer();
        $this->sessions = new RecordingSessions();
        $this->consents = new RecordingConsents();
        $this->issue = new IssueAccessInvitationUseCase(
            $this->accounts,
            $this->links,
            new SequenceAccessTokens(),
            $this->mailer,
            new FrozenClock(self::NOW),
            168,
            60,
        );
        $this->accounts->add(7, 'Anna@Example.invalid', active: false, pending: true);
        $this->accounts->add(8, 'boris@example.invalid', active: true, pending: false);
    }

    public function testInvitationStoresOnlyTheHashAndMailsTheToken(): void
    {
        $state = $this->issue->execute(7, 1);

        [$kind, $mail] = $this->mailer->sent[0];
        $link = $this->links->links['7:invite'];
        self::assertSame('invite', $kind);
        self::assertSame(AccessLink::hashToken($mail->token), $link->tokenHash);
        self::assertNotSame($mail->token, $link->tokenHash);
        self::assertSame(self::NOW + 168 * 3600, $link->expiresAt);
        self::assertSame('sent', $state->state);
        self::assertSame(1, $link->issuedBy);
    }

    public function testRepeatedInvitationRespectsCooldownUnlessForced(): void
    {
        $this->issue->execute(7, 1);
        $firstHash = $this->links->links['7:invite']->tokenHash;
        try {
            $this->issue->execute(7, 1);
            self::fail('Cooldown must reject the repeat.');
        } catch (HttpException $error) {
            self::assertSame('RATE_LIMITED', $error->getMessage());
            self::assertSame(429, $error->getCode());
        }
        $this->issue->execute(7, 1, force: true);

        self::assertNotSame($firstHash, $this->links->links['7:invite']->tokenHash);
        self::assertCount(2, $this->mailer->sent);
    }

    public function testActiveAccountCannotBeInvited(): void
    {
        $this->expectExceptionObject(new HttpException('INVITATION_NOT_AVAILABLE', 409));

        $this->issue->execute(8, 1);
    }

    public function testInvitationPreviewMasksTheAddress(): void
    {
        $this->issue->execute(7, 1);
        $token = $this->mailer->sent[0][1]->token;

        $preview = $this->get()->execute($token);

        self::assertSame('A***@Example.invalid', $preview->maskedEmail);
        self::assertSame('Анна', $preview->name);
    }

    public function testExpiredUnknownAndForeignPurposeLinksAreRejected(): void
    {
        $this->issue->execute(7, 1);
        $token = $this->mailer->sent[0][1]->token;
        $this->assertHttpError('LINK_NOT_FOUND', 404, fn() => $this->get()->execute(str_repeat('z', 43)));

        $this->links->age(7, AccessLinkPurposeEnum::INVITE, 168 * 3600);
        $this->assertHttpError('LINK_EXPIRED', 410, fn() => $this->get()->execute($token));
    }

    public function testAcceptSetsPasswordActivatesOnceAndStartsSession(): void
    {
        $this->issue->execute(7, 1);
        $token = $this->mailer->sent[0][1]->token;
        $accept = $this->accept();

        $this->assertHttpError('PASSWORD_WEAK', 422, fn() => $accept->execute(new AcceptInvitationInputDto($token, 'short', self::consents())));
        $this->assertHttpError('PASSWORD_WEAK', 422, fn() => $accept->execute(new AcceptInvitationInputDto($token, 'anna@example.invalid', self::consents())));
        self::assertFalse($this->accounts->accounts[7]->active);

        $login = $accept->execute(new AcceptInvitationInputDto($token, 'correct horse battery', self::consents()));

        self::assertTrue($this->accounts->accounts[7]->active);
        self::assertFalse($this->accounts->accounts[7]->pending);
        self::assertTrue(password_verify('correct horse battery', $this->accounts->accounts[7]->passwordHash));
        self::assertSame($login->token, $this->sessions->tokens[7]);
        self::assertNotNull($this->links->links['7:invite']->usedAt);
        self::assertSame([[ConsentContextEnum::STAFF, 7, 1]], $this->consents->records);
        $this->assertHttpError('LINK_USED', 410, fn() => $accept->execute(new AcceptInvitationInputDto($token, 'another good password', self::consents())));
    }

    public function testAcceptWithoutConsentKeepsTheInvitationPending(): void
    {
        $this->issue->execute(7, 1);
        $token = $this->mailer->sent[0][1]->token;

        $this->assertHttpError('CONSENT_REQUIRED', 422, fn() => $this->accept()->execute(new AcceptInvitationInputDto($token, 'correct horse battery', [])));

        self::assertFalse($this->accounts->accounts[7]->active);
        self::assertNull($this->links->links['7:invite']->usedAt);
    }

    public function testResetAnswersTheSameForUnknownAddressAndSendsOnlyToKnown(): void
    {
        $reset = $this->requestReset();

        $reset->execute(new PasswordResetInputDto('nobody@example.invalid'));
        self::assertSame([], $this->mailer->sent);

        $reset->execute(new PasswordResetInputDto(' BORIS@example.invalid '));
        self::assertSame('reset', $this->mailer->sent[0][0]);
        self::assertSame(self::NOW + 3600, $this->links->links['8:reset']->expiresAt);

        $reset->execute(new PasswordResetInputDto('boris@example.invalid'));
        self::assertCount(1, $this->mailer->sent, 'cooldown silently skips a repeat');
    }

    public function testResetForPendingAccountResendsTheInvitation(): void
    {
        $this->requestReset()->execute(new PasswordResetInputDto('anna@example.invalid'));

        self::assertSame('invite', $this->mailer->sent[0][0]);
        self::assertArrayNotHasKey('7:reset', $this->links->links);
    }

    public function testConfirmResetReplacesPasswordAndSession(): void
    {
        $this->sessions->tokens[8] = 'stolen-session';
        $this->requestReset()->execute(new PasswordResetInputDto('boris@example.invalid'));
        $token = $this->mailer->sent[0][1]->token;
        $confirm = $this->confirmReset();

        $this->assertHttpError('LINK_NOT_FOUND', 404, fn() => $this->get()->execute($token));
        $login = $confirm->execute(new ConfirmPasswordResetInputDto($token, 'brand new password'));

        self::assertTrue(password_verify('brand new password', $this->accounts->accounts[8]->passwordHash));
        self::assertSame($login->token, $this->sessions->tokens[8]);
        self::assertNotSame('stolen-session', $this->sessions->tokens[8]);
        $this->assertHttpError('LINK_USED', 410, fn() => $confirm->execute(new ConfirmPasswordResetInputDto($token, 'brand new password 2')));
    }

    public function testChangePasswordChecksTheCurrentOne(): void
    {
        $change = new ChangePasswordUseCase($this->accounts, new PasswordPolicy(), new ImmediateTransaction());

        $this->assertHttpError('CURRENT_PASSWORD_INVALID', 422, fn() => $change->execute(8, new ChangePasswordInputDto('wrong', 'long enough password')));
        $this->assertHttpError('PASSWORD_WEAK', 422, fn() => $change->execute(8, new ChangePasswordInputDto('old-password-1', 'short')));
        $change->execute(8, new ChangePasswordInputDto('old-password-1', 'long enough password'));

        self::assertTrue(password_verify('long enough password', $this->accounts->accounts[8]->passwordHash));
    }

    public function testPolicyAndMask(): void
    {
        $policy = new PasswordPolicy();
        self::assertFalse($policy->isAcceptable('123456789', 'a@example.invalid'));
        self::assertFalse($policy->isAcceptable('          ', 'a@example.invalid'));
        self::assertFalse($policy->isAcceptable(str_repeat('x', 129), 'a@example.invalid'));
        self::assertTrue($policy->isAcceptable('десять букв', 'a@example.invalid'));
        self::assertSame('i***@example.invalid', (new EmailMask())->mask('ivan@example.invalid'));
        self::assertSame('***', (new EmailMask())->mask('broken'));
    }

    private function get(): GetAccessInvitationUseCase
    {
        return new GetAccessInvitationUseCase($this->links, $this->accounts, new AccessLinkGuard(), new EmailMask(), new FrozenClock(self::NOW));
    }

    private function accept(): AcceptAccessInvitationUseCase
    {
        return new AcceptAccessInvitationUseCase(
            $this->links,
            $this->accounts,
            new AccessLinkGuard(),
            new PasswordPolicy(),
            $this->sessionIssuer(),
            new FrozenClock(self::NOW),
            new ImmediateTransaction(),
            $this->consents,
        );
    }

    /** @return list<AcceptedDocumentDto> */
    private static function consents(): array
    {
        return [new AcceptedDocumentDto('staff-consent', '2026-09-25')];
    }

    private function requestReset(): RequestPasswordResetUseCase
    {
        return new RequestPasswordResetUseCase(
            $this->accounts,
            $this->links,
            new SequenceAccessTokens(),
            $this->mailer,
            $this->issue,
            new FrozenClock(self::NOW),
            new ImmediateTransaction(),
            60,
            60,
        );
    }

    private function confirmReset(): ConfirmPasswordResetUseCase
    {
        return new ConfirmPasswordResetUseCase(
            $this->links,
            $this->accounts,
            new AccessLinkGuard(),
            new PasswordPolicy(),
            $this->sessionIssuer(),
            new FrozenClock(self::NOW),
            new ImmediateTransaction(),
        );
    }

    private function sessionIssuer(): SessionIssuer
    {
        return new SessionIssuer(new SequenceSessionTokens(), $this->sessions, new FrozenClock(self::NOW), 24);
    }

    private function assertHttpError(string $code, int $status, callable $call): void
    {
        try {
            $call();
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame($code, $error->getMessage());
            self::assertSame($status, $error->getCode());
        }
    }
}
