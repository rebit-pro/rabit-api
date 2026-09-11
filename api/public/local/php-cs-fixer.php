<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use Rebit\Dev\PhpCsFixer\Rules\CustomVisibilityRequiredFixer;
use Rebit\Dev\PhpCsFixer\Rules\DebugStatementRemoverFixer;

return (new Config())
    ->setCacheFile(__DIR__ . '/../../var/.php-cs-fixer.cache')
    ->setRiskyAllowed(true)
    ->registerCustomFixers([
        new CustomVisibilityRequiredFixer(),
        new DebugStatementRemoverFixer(),
    ])
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        'no_superfluous_phpdoc_tags' => true,
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'none'],
        'array_syntax' => ['syntax' => 'short'],
        'protected_to_private' => false,
        'native_function_invocation' => false,
        'native_constant_invocation' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'function_declaration' => ['closure_function_spacing' => 'none', 'closure_fn_spacing' => 'none'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'ordered_class_elements' => false,
        'ordered_interfaces' => false,
        'ordered_traits' => false,
        'ordered_imports' => false,
        'php_unit_test_class_requires_covers' => false,
        'blank_line_before_statement' => [
            'statements' => ['return'],
        ],
        'visibility_required' => false,
        'Custom/visibility_required' => true,
        'Custom/debug_statement_remover' => false,
    ])
    ->setFinder(
        Finder::create()
            ->in(__DIR__)
            ->exclude([
                'vendor',
                'modules/sprint.migration',
                'php_interface/migrations',
            ]),
    )
;
