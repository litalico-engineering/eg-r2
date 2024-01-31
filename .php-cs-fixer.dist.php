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
        'linebreak_after_opening_tag' => true, // Put a line break after the start tag so that there is no description on the line of the start tag
        'no_leading_namespace_whitespace' => true, // namespaceの前にスペースがあったとき削除します

        'no_unused_imports' => true, // 使用していないuse文は削除

        // comment ------------------
        'align_multiline_comment' => true, // マルチコメントを修正 /* */

        'no_empty_comment' => true, // 空コメント削除
        'no_empty_phpdoc' => true, // 空コメント削除
        'no_empty_statement' => true, // ;;とか削除

        // array ------------------
        'array_syntax' => ['syntax' => 'short'], // array() を[]にする
        'array_indentation' => true, // 配列のインデント揃える

        'no_superfluous_elseif' => true, // list関数の余計なカンマを削除
        'no_multiline_whitespace_around_double_arrow' => true, // =>の前後で複数行になるスペースを禁止します
        'no_trailing_comma_in_singleline_array' => true, // 単一行で記述する配列で余計なカンマを削除します
        'no_whitespace_before_comma_in_array' => true, // 配列内で、カンマの前にスペースを禁止します

        // syntax ------------------
        'elseif' => true, // 'else if' to 'elseif'
        'compact_nullable_typehint' => true, // '? int' to '?int'
        'function_typehint_space' => true, // 関数の返り値の型宣言にスペースが抜けていると補完する

        // space, line ------------------

        // 色んな空白行削除
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_break_comment' => false,

        'method_argument_space' => [
            'ensure_fully_multiline' => true, // 複数行に渡るときには1行に引数一つとする
            'keep_multiple_spaces_after_comma' => false, // 複数のスペースを許容する(falseで複数スペースを単一にする)
        ],

        // 余計な改行を削除
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'use'],
        ],

        'no_whitespace_in_blank_line' => true, // 空白行でスペースを禁止

        // 特定のキーワードの前に改行を入れる
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

        // コロンの後ろに空白を1つ入れる
        'return_type_declaration' => true,

        // R2でのルール

        '@PHP80Migration:risky' => true,
        '@PHP81Migration' => true,
        '@PhpCsFixer:risky' => true,

        // namespaceの前に1つ空行を入れる かんたん請求では開けていなかった。
        'no_blank_lines_before_namespace' => false,
        'single_blank_line_before_namespace' => true,

        // セミコロンの位置
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],

        // 配列周り
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'single_space', // かんたん請求では、'align', => は揃える
                '=' => 'single_space', // かんたん請求では、null, = は揃えない
            ],
        ],

        // コメントの前にスペースを入れる
        'single_line_comment_spacing' => true,

        // phpdocのインデント
        'phpdoc_align' => [
            'align' => 'left',
        ],

        // なくても良いphpdocを非表示にするかどうか
        'no_superfluous_phpdoc_tags' => false,

        // phpdocの@packageをなくすかどうか
        'phpdoc_no_package' => false,
        'general_phpdoc_annotation_remove' => [
            'annotations' => ['author'],
        ],

        // 不要なnamespace設定を削除する
        'fully_qualified_strict_types' => true,

        // voidを付与する
        'void_return' => true,

        // 標準関数のnamespaceをきれいにする
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        // is_nullは使わず === とする
        'is_null' => true,

        // ヨーダ記法の設定
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],

        // 匿名クラスでの{}の扱いについて
        'curly_braces_position' => [
            'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],

        // テストで@testを利用する
        'php_unit_test_annotation' => [
            'style' => 'annotation',
        ],

        // assetメソッドに関して厳密にするかどうかは、開発者に委ねる
        'php_unit_strict' => [],

        // === を比較で矯正しない。開発者に委ねる
        'strict_comparison' => false,

        // 1行の場合末尾の,を削除する。
        'no_trailing_comma_in_singleline' => true,

        // useのソートを行う
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => [
                'const',
                'class',
                'function',
            ],
        ],

        // use にてグループ分けをしない
        'blank_line_between_import_groups' => false,

        // traitのソートはしない
        'ordered_traits' => false,

        // list関数を利用する
        'list_syntax' => [
            'syntax' => 'long',
        ],

        // シングルクオートを利用する
        'single_quote' => true,

        // static function, static fun が使えるときはそうする
        'static_lambda' => true,

        // 文字列内における変数転換の設定
        'simple_to_complex_string_variable' => true,

        // function, fn あとのスペース設定
        'function_declaration' => [
            'closure_function_spacing' => 'one',
            'closure_fn_spacing' => 'one',
        ],

        // PHPUnitでのメソッドの呼び出し方
        'php_unit_test_case_static_method_calls' => [
            'call_type' => 'self',
        ],

        // PSR12のルールに準拠し、use HogeTrait は1行ずつにする
        'single_trait_insert_per_statement' => true,

        // データ提供者名は試験名と一致していなければならない。
        'php_unit_data_provider_name' => false,
    ])
    ->setFinder($finder);
