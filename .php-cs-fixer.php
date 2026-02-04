<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP84Migration' => true,
        '@PSR12' => true,

        // Modern PHP
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,

        // Arrays
        'array_syntax' => ['syntax' => 'short'],
        'no_whitespace_before_comma_in_array' => true,
        'trim_array_spaces' => true,
        'array_indentation' => true,

        // Imports
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Classes
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'property' => 'one',
            ],
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public_static',
                'property_protected_static',
                'property_private_static',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public_static',
                'method_protected_static',
                'method_private_static',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        'final_class' => false, // We decide when to use final
        'self_static_accessor' => true,

        // Functions
        'return_type_declaration' => ['space_before' => 'none'],
        'void_return' => true,
        'nullable_type_declaration_for_default_null_value' => true,

        // Spacing
        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'one'],
        'not_operator_with_successor_space' => false,
        'single_line_empty_body' => true,

        // Comments
        'no_empty_comment' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],

        // Control structures
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'simplified_if_return' => true,
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,

        // Safety
        'no_alias_functions' => true,
        'no_mixed_echo_print' => ['use' => 'echo'],

        // Cleanup
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,

        // Match expression (PHP 8.0+)
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/storage/cache/.php-cs-fixer.cache');
