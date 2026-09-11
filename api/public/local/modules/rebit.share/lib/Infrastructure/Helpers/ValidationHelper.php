<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Helpers;

use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Interface\DtoInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidationHelper
{
    private static ?ValidatorInterface $validator = null;

    private static function getValidator(): ValidatorInterface
    {
        if (null === self::$validator) {
            self::$validator = Validation::createValidatorBuilder()
                ->enableAnnotationMapping()
                ->getValidator()
            ;
        }

        return self::$validator;
    }

    /**
     * @throws ValidationHttpException
     */
    public static function validate(DtoInterface $dto): void
    {
        $violations = self::getValidator()->validate($dto);
        if ($violations->count() > 0) {
            throw new ValidationHttpException(
                sprintf('Validation failed: %s', (string)$violations),
            );
        }
    }
}
