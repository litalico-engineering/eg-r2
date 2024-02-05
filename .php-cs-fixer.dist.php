<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->exclude([
    ])
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        'strict_param' => true,

        // header ------------------
        'linebreak_after_opening_tag' => true, // Ensure line break after opening tag
        'no_leading_namespace_whitespace' => true, // If there is a space before namespace, delete it.

        'no_unused_imports' => true, // Remove unused imports

        // comment ------------------
        'align_multiline_comment' => true, // Align multiline comments /* */

        'no_empty_comment' => true, // Remove empty comments
        'no_empty_phpdoc' => true, // Remove empty PHPDoc
        'no_empty_statement' => true, // Remove empty statements

        // array ------------------
        'array_syntax' => ['syntax' => 'short'], // Use short array syntax
        'array_indentation' => true, // Align array indentation

        'no_superfluous_elseif' => true, // Simplify "else if" to "elseif"
        'no_multiline_whitespace_around_double_arrow' => true, // Remove whitespace around double arrow
        'no_trailing_comma_in_singleline_array' => true, // Remove trailing commas in single-line arrays
        'no_whitespace_before_comma_in_array' => true, // No spaces before commas in arrays

        // syntax ------------------
        'elseif' => true, // Convert 'else if' to 'elseif'
        'compact_nullable_typehint' => true, // Compact nullable typehint
        'function_typehint_space' => true, // Ensure space in function return type declaration

        // space, line ------------------

        // Delete various blank lines
        'no_blank_lines_after_class_opening' => true,   // No blank lines after class opening
        'no_blank_lines_after_phpdoc' => true,  //  No blank lines after PHPDoc
        'no_break_comment' => false,    // Allow break comments

        'method_argument_space' => [
            'ensure_fully_multiline' => true, // One argument per line in multiline
            'keep_multiple_spaces_after_comma' => false, // Single space after comma
        ],

        // No whitespace in blank lines
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'use'],
        ],

        'no_whitespace_in_blank_line' => true, // Blank lines prohibit spaces

        // Put a line break before certain keywords
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'return',
                'throw',
                'try',
            ],
        ],

        // Ensure space after colon in return type declaration
        'return_type_declaration' => true,

        '@PHP80Migration:risky' => true,
        '@PHP81Migration' => true,
        '@PhpCsFixer:risky' => true,

        'no_blank_lines_before_namespace' => false, // Allow blank lines before namespace
        'single_blank_line_before_namespace' => true,   // Single blank line before namespace

        // Semicolon position
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],

        // Arrangement
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'single_space',
                '=' => 'single_space',
            ],
        ],

        // Ensure space before single line comment
        'single_line_comment_spacing' => true,

        // Indentation of PHPDoc
        'phpdoc_align' => [
            'align' => 'left',
        ],

        // Allow superfluous PHPDoc tags
        'no_superfluous_phpdoc_tags' => false,

        // Allow @package in PHPDoc
        'phpdoc_no_package' => false,
        'general_phpdoc_annotation_remove' => [
            'annotations' => ['author'],
        ],

        // Use fully qualified strict types
        'fully_qualified_strict_types' => true,

        // Use void return type
        'void_return' => true,

        // Clean up namespace in standard functions
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        // Use === instead of is_null
        'is_null' => true,

        // Yoda Notation Settings
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],

        // Handling of {} in anonymous classes
        'curly_braces_position' => [
            'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],

        // Using @test in testing
        'php_unit_test_annotation' => [
            'style' => 'annotation',
        ],

        // Developer decides strictness of assert method
        'php_unit_strict' => [],

        // Developer decides strictness of comparison
        'strict_comparison' => false,

        // Remove trailing comma in single line
        'no_trailing_comma_in_singleline' => true,

        // Sort imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => [
                'const',
                'class',
                'function',
            ],
        ],

        // No import grouping
        'blank_line_between_import_groups' => false,

        // No trait sorting
        'ordered_traits' => false,

        // Use long list syntax
        'list_syntax' => [
            'syntax' => 'long',
        ],

        // Use single quotes
        'single_quote' => true,

        // Use static lambda when possible
        'static_lambda' => true,

        // Convert simple to complex string variables
        'simple_to_complex_string_variable' => true,

        // Space after function declaration
        'function_declaration' => [
            'closure_function_spacing' => 'one',
            'closure_fn_spacing' => 'one',
        ],

        // Use 'self' for PHPUnit method calls
        'php_unit_test_case_static_method_calls' => [
            'call_type' => 'self',
        ],

        // One trait per statement
        'single_trait_insert_per_statement' => true,

        // No data provider name enforcement
        'php_unit_data_provider_name' => false,
    ])
    ->setFinder($finder);
