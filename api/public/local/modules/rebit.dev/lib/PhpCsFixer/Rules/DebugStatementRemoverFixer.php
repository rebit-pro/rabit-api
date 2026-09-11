<?php

namespace Rebit\Dev\PhpCsFixer\Rules;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Tokens;

class DebugStatementRemoverFixer extends AbstractFixer
{
    private const array DEBUG_FUNCTIONS = ['var_dump', 'print_r', 'dd', 'dump'];

    public function getName(): string
    {
        return 'Custom/debug_statement_remover';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Removes calls to debug functions like var_dump(), print_r(), dd(), dump()',
            [new CodeSample("<?php\nvar_dump(\$foo);\nprint_r(\$bar);\ndd('test');\n")],
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        foreach ($tokens as $token) {
            if (
                $token->isGivenKind(T_STRING)
                && in_array($token->getContent(), self::DEBUG_FUNCTIONS, true)
            ) {
                return true;
            }
        }

        return false;
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if (
                !$token->isGivenKind(T_STRING)
                || false === in_array($token->getContent(), self::DEBUG_FUNCTIONS, true)
            ) {
                continue;
            }

            $nextIndex = $tokens->getNextMeaningfulToken($index);
            if (null === $nextIndex || '(' !== $tokens[$nextIndex]->getContent()) {
                continue;
            }

            $endIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $nextIndex);
            $semicolonIndex = $tokens->getNextTokenOfKind($endIndex, [';']);

            if (null === $semicolonIndex) {
                continue;
            }

            $startIndex = $tokens->getPrevNonWhitespace($index);
            $endClearIndex = $tokens->getNextNonWhitespace($semicolonIndex);

            for ($i = $startIndex + 1; $i < $endClearIndex; ++$i) {
                $tokens->clearAt($i);
            }
        }
    }

    public function isRisky(): bool
    {
        return true;
    }
}
