<?php

declare(strict_types=1);

namespace Morefoto\Legal\Tests\Unit;

use Morefoto\Legal\Application\Consent\Service\ConsentRecorder;
use Morefoto\Legal\Application\Consent\UseCase\AcceptStaffConsentsUseCase;
use Morefoto\Legal\Application\Consent\UseCase\GetPendingStaffConsentsUseCase;
use Morefoto\Legal\Application\Document\Mapper\LegalDocumentMapper;
use Morefoto\Legal\Domain\Document\Entity\LegalDocumentVersion;
use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;
use Morefoto\Legal\Domain\Document\Repository\LegalDocumentCatalogInterface;
use Morefoto\Legal\Domain\Document\Service\ConsentRequirementPolicy;
use Morefoto\Legal\Domain\Document\Service\ReconsentPolicy;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class ConsentRecorderTest extends TestCase
{
    private InMemoryConsentJournal $journal;

    protected function setUp(): void
    {
        $this->journal = new InMemoryConsentJournal();
    }

    public function testOrderRecordsBothCurrentDocumentsOnce(): void
    {
        $recorder = $this->recorder();
        $accepted = [new AcceptedDocumentDto('offer', '2026-10-01'), new AcceptedDocumentDto('buyer-consent', '2026-09-25'), new AcceptedDocumentDto('privacy', '2026-09-25')];

        $recorder->record(ConsentContextEnum::ORDER, 7, $accepted);
        $recorder->record(ConsentContextEnum::ORDER, 7, $accepted);

        self::assertSame(['order:7:buyer-consent:2026-09-25', 'order:7:offer:2026-10-01'], array_keys($this->journal->rows));
    }

    public function testMissingOrOutdatedDocumentIsRejectedWithCurrentVersions(): void
    {
        foreach ([[], [new AcceptedDocumentDto('buyer-consent', '2026-09-25'), new AcceptedDocumentDto('offer', '2026-09-25')]] as $accepted) {
            try {
                $this->recorder()->record(ConsentContextEnum::ORDER, 7, $accepted);
                self::fail('CONSENT_REQUIRED expected.');
            } catch (HttpException $error) {
                self::assertSame('CONSENT_REQUIRED', $error->getMessage());
                self::assertSame(422, $error->getCode());
                self::assertSame(
                    [['code' => 'buyer-consent', 'version' => '2026-09-25'], ['code' => 'offer', 'version' => '2026-10-01']],
                    $error->getDetails()['documents'],
                );
            }
        }
        self::assertSame([], $this->journal->rows);
    }

    public function testStaffPendingDisappearsAfterAcceptance(): void
    {
        $recorder = $this->recorder();
        $pending = new GetPendingStaffConsentsUseCase($recorder, $this->journal, new ReconsentPolicy(), new LegalDocumentMapper());

        self::assertSame(['staff-consent'], array_map(static fn(object $document): string => $document->code, $pending->execute(3)));
        self::assertSame([], (new AcceptStaffConsentsUseCase($recorder, $pending))->execute(3, [new AcceptedDocumentDto('staff-consent', '2026-09-25')]));
        self::assertSame(['staff-consent'], array_map(static fn(object $document): string => $document->code, $pending->execute(4)));
    }

    public function testReconsentIsAskedOnlyForVersionsThatRequireIt(): void
    {
        $policy = new ReconsentPolicy();
        $minor = new LegalDocumentVersion(LegalDocumentEnum::STAFF_CONSENT, '2026-11-01', 'Согласие', '2026-11-01', false);
        $major = new LegalDocumentVersion(LegalDocumentEnum::STAFF_CONSENT, '2026-12-01', 'Согласие', '2026-12-01', true);

        self::assertSame([], $policy->pending([$minor], ['staff-consent' => ['2026-09-25']]));
        self::assertSame([$minor], $policy->pending([$minor], []));
        self::assertSame([$major], $policy->pending([$major], ['staff-consent' => ['2026-09-25']]));
        self::assertSame([], $policy->pending([$major], ['staff-consent' => ['2026-09-25', '2026-12-01']]));
    }

    private function recorder(): ConsentRecorder
    {
        $catalog = $this->createStub(LegalDocumentCatalogInterface::class);
        $catalog->method('current')->willReturnCallback(static fn(LegalDocumentEnum $code): LegalDocumentVersion => new LegalDocumentVersion(
            $code,
            LegalDocumentEnum::OFFER === $code ? '2026-10-01' : '2026-09-25',
            $code->value,
            '2026-09-25',
            true,
        ));
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-25T12:00:00+03:00'));

        return new ConsentRecorder($catalog, new ConsentRequirementPolicy(), $this->journal, $clock);
    }
}
