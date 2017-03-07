<?php
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);
return PhpCsFixer\Config::create()
    ->setUsingCache(false)
    ->setRules(
        array(
            '@PSR2' => true,
            'no_blank_lines_after_phpdoc' => true,
            'phpdoc_add_missing_param_annotation' => true,
            'phpdoc_align' => true,
            'phpdoc_separation' => true,
        )
    )
    ->setFinder($finder);
