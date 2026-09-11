<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Contract;

use Rebit\Auth\Application\Auth\Dto\Request\LoginCaptchaRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

interface CaptchaVerifierInterface
{
    /**
     * @throws HttpException
     */
    public function verify(?LoginCaptchaRequestDto $captcha): void;
}
