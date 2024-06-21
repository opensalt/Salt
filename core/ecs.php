<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        // __DIR__.'/tests',
    ])

    ->withParallel()

    // add a single rule
    ->withRules([
        \PhpCsFixer\Fixer\Import\NoUnusedImportsFixer::class,
    ])

    ->withSets([
    ])

    // add sets - group of rules
    ->withPreparedSets(
        psr12: true,
        namespaces: true,
        cleanCode: true,
        // controlStructures: true,
        // strict: true,
    )

   ->withSkip([
       \PhpCsFixer\Fixer\Operator\ConcatSpaceFixer::class => null,
       \PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer::class => null,
       // \PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer::class => null,
       // \PhpCsFixer\Fixer\Phpdoc\PhpdocSummaryFixer::class => null,
       // \PhpCsFixer\Fixer\Phpdoc\PhpdocToCommentFixer::class => null,
       // \PhpCsFixer\Fixer\Phpdoc\PhpdocVarWithoutNameFixer::class => null,
       \PhpCsFixer\Fixer\Whitespace\StatementIndentationFixer::class => null,
       \PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer::class => null,
   ])
;
