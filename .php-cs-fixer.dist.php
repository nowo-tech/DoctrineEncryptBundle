<?php

declare(strict_types=1);

/**
 * PHP-CS-Fixer configuration (PSR-12 + Symfony canonical).
 * Standard for Nowo bundles. Run: make cs-check | make cs-fix
 */
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        // Base: PSR-12 + Symfony convention
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // Strict types everywhere
        'declare_strict_types' => true,

        // Imports: order (class, function, const; alphabetical), one per line, no unused
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'single_import_per_statement' => true,
        'no_unused_imports' => true,
        'no_leading_import_slash' => true,
        'single_line_after_imports' => true,

        // Arrays: short syntax, trailing comma in multiline
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'arrays', 'match'],
        ],

        // Spacing & formatting
        'concat_space' => ['spacing' => 'one'],
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=>' => 'align_single_space_minimal',
                '=' => 'align_single_space_minimal',
            ],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'single_blank_line_at_eof' => true,
        'blank_line_after_opening_tag' => true,

        // Control structures
        'no_useless_else' => true,
        'no_useless_return' => true,
        'simplified_if_return' => true,
        'single_line_throw' => true,
        'no_superfluous_elseif' => true,
        'switch_continue_to_break' => true,

        // PHPDoc: consistent order and spacing, remove redundant
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'alpha',
        ],
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'remove_inheritdoc' => false,
        ],
        'phpdoc_align' => [
            'align' => 'left',
            'tags' => ['param', 'property', 'return', 'throws', 'type', 'var'],
        ],
        'phpdoc_no_empty_return' => false,
        'phpdoc_add_missing_param_annotation' => false,

        // Modern PHP / clarity
        'no_null_property_initialization' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
        'modernize_types_casting' => true,
        'no_short_bool_cast' => true,
        'explicit_string_variable' => true,

        // FQCN → use + short name
        'fully_qualified_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
            ->exclude(['vendor', 'var', 'coverage', '.phpunit.cache'])
            // tests/ is included (do not exclude)
    );
