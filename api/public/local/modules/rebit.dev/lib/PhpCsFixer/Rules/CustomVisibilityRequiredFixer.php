<?php

declare(strict_types=1);

namespace Rebit\Dev\PhpCsFixer\Rules;

use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

/**
 * Правило-декоратор на основе стандартного, с патчем для игнора легаси в install модулях Битрикса.
 * Стандартное правило нужно отключить в конфигах.
 *
 * @see VisibilityRequiredFixer
 */
final readonly class CustomVisibilityRequiredFixer implements FixerInterface, ConfigurableFixerInterface
{
    public function __construct(
        private VisibilityRequiredFixer $original = new VisibilityRequiredFixer(),
    ) {}

    public function getName(): string
    {
        return 'Custom/visibility_required';
    }

    public function isCandidate(Tokens $tokens): bool
    {
        foreach ($tokens->findGivenKind(T_CLASS) as $classIndex => $_) {
            $classNameIndex = $tokens->getNextMeaningfulToken($classIndex);
            if (null === $classNameIndex) {
                continue;
            }

            $extendsIndex = $tokens->getNextMeaningfulToken($classNameIndex);
            if (null === $extendsIndex || 'extends' !== $tokens[$extendsIndex]->getContent()) {
                continue;
            }

            $parentIndex = $tokens->getNextMeaningfulToken($extendsIndex);
            if (null !== $parentIndex && 'CModule' === $tokens[$parentIndex]->getContent()) {
                return false;
            }
        }

        return $this->original->isCandidate($tokens);
    }

    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        $this->original->fix($file, $tokens);
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return $this->original->getDefinition();
    }

    public function getPriority(): int
    {
        return $this->original->getPriority();
    }

    public function isRisky(): bool
    {
        return $this->original->isRisky();
    }

    public function supports(\SplFileInfo $file): bool
    {
        return $this->original->supports($file);
    }

    public function configure(array $configuration): void
    {
        $this->original->configure($configuration);
    }

    public function getConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return $this->original->getConfigurationDefinition();
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->original->{$method}(...$arguments);
    }
}
