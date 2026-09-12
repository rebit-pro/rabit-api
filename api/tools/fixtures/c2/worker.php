<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Organization\Application\Institution\Dto\InstitutionMutationInputDto;
use Morefoto\Organization\Application\Institution\UseCase\SaveInstitutionUseCase;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Rebit\Auth\Application\Auth\Contract\CaptchaVerifierInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\Dto\Request\LoginCaptchaRequestDto;
use Rebit\Auth\Application\Auth\Dto\Request\LoginRequestDto;
use Rebit\Auth\Application\Auth\UseCase\LoginUseCase;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Auth\Infrastructure\Adapter\BitrixAuthTransaction;
use Rebit\Auth\Infrastructure\Adapter\SystemClock;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionVersionConflictException;

$documentRoot = $argv[1] ?? '';
$job = json_decode((string)file_get_contents($argv[2] ?? ''), true, 32, JSON_THROW_ON_ERROR);
require __DIR__ . '/connect.php';
$connectionId = (int)Application::getConnection()->query('SELECT CONNECTION_ID() id')->fetch()['id'];
file_put_contents($job['ready'], (string)$connectionId);
try {
    if ('mutate' === $job['action']) {
        $input = new InstitutionMutationInputDto($job['key'], null, null, $job['revision'], true, $job['target'], false, null, $job['signature'], true);
        $result = ServiceLocator::getInstance()->get(SaveInstitutionUseCase::class)->execute($job['actor'], $job['bearer'], new InstitutionId($job['institution']), $input);
        $result = ['status' => 200, 'revision' => $result->revision, 'signature' => $result->assignmentSignature];
    } else {
        $captcha = new class implements CaptchaVerifierInterface {
            public function verify(?LoginCaptchaRequestDto $dto): void {}
        };
        $generator = new class($job) implements TokenGeneratorInterface {
            public function __construct(private readonly array $job) {}

            public function generate(): string
            {
                if (isset($this->job['locked'])) {
                    file_put_contents($this->job['locked'], 'locked');
                    $deadline = microtime(true) + 15;
                    while (!is_file($this->job['release'])) {
                        if (microtime(true) >= $deadline) {
                            throw new RuntimeException('Login gate timed out.');
                        }
                        usleep(10000);
                    }
                }

                return $this->job['issuedToken'];
            }
        };
        $login = new LoginUseCase(new UserRepository(), $generator, $captcha, 24, new SystemClock(), new BitrixAuthTransaction());
        $login->execute(new LoginRequestDto($job['email'], 'Native-C2-test-password-123', new LoginCaptchaRequestDto('fixture', 'fixture', 'fixture', 'fixture')));
        $result = ['status' => 200];
    }
} catch (Throwable $exception) {
    // This worker calls the use case directly; classify the domain conflict as its API outcome.
    $status = $exception instanceof InstitutionVersionConflictException ? 409 : $exception->getCode();
    $result = ['status' => $status, 'type' => $exception::class, 'message' => $exception->getMessage()];
}
$result['transactionLevel'] = (new ReflectionProperty(Application::getConnection(), 'transactionLevel'))->getValue(Application::getConnection());
file_put_contents($job['result'] . '.tmp', json_encode($result, JSON_THROW_ON_ERROR));
rename($job['result'] . '.tmp', $job['result']);
